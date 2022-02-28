var J=Object.defineProperty,K=Object.defineProperties;var Q=Object.getOwnPropertyDescriptors;var j=Object.getOwnPropertySymbols;var W=Object.prototype.hasOwnProperty,X=Object.prototype.propertyIsEnumerable;var L=(e,o,r)=>o in e?J(e,o,{enumerable:!0,configurable:!0,writable:!0,value:r}):e[o]=r,E=(e,o)=>{for(var r in o||(o={}))W.call(o,r)&&L(e,r,o[r]);if(j)for(var r of j(o))X.call(o,r)&&L(e,r,o[r]);return e},R=(e,o)=>K(e,Q(o));import{B as k,r as S,t as P,e as i,y as z,f as u,q as p,w as n,h as a,k as t,z as M,g as q,A as O,F as Y,E as $,l as _,d as Z,u as ee,o as le,i as A,j as C,p as oe,m as ae}from"./vendor.51c5b88d.js";import{_ as G,a as V}from"./index.d73ba3c8.js";const ne={name:"DialogAssignExam",props:{reload:Function},setup(e,o){const r=k(null),l=S({loading:!1,matchExams:[],visible:!1,formData:{uid:0,exam_id:"",time_range:[]},rules:{exam_id:[{required:"true"}]}}),v=async()=>{let d=await V.listUserMatchExams({uid:l.formData.uid});l.matchExams=d.data},g=d=>{l.formData.uid=d,l.matchExams.length==0&&(l.loading=!0,v(),l.loading=!1),l.visible=!0},s=()=>{r.value.validate(async d=>{if(d){let m=await V.storeExamUser(l.formData);l.visible=!1,$.success(m.msg),e.reload&&e.reload()}})};return R(E({},P(l)),{handleSubmit:s,formRef:r,open:g})}},te=a("div",{class:"time-range-help-text"},"If the time range is not specified, the exam's own configured time range will be used.",-1),se={class:"dialog-footer"},ie=_("Cancel"),de=_("Save");function re(e,o,r,l,v,g){const s=i("el-option"),d=i("el-select"),m=i("el-form-item"),f=i("el-date-picker"),b=i("el-form"),y=i("el-button"),h=i("el-dialog"),U=z("loading");return u(),p(h,{title:"Assign exam to user",modelValue:e.visible,"onUpdate:modelValue":o[3]||(o[3]=c=>e.visible=c),center:"","close-on-click-modal":!1},{footer:n(()=>[a("span",se,[t(y,{onClick:o[2]||(o[2]=c=>e.visible=!1)},{default:n(()=>[ie]),_:1}),t(y,{type:"primary",onClick:l.handleSubmit},{default:n(()=>[de]),_:1},8,["onClick"])])]),default:n(()=>[M((u(),p(b,{model:e.formData,"label-width":"100px",ref:"formRef",rules:e.rules},{default:n(()=>[t(m,{label:"Exam",prop:"exam_id"},{default:n(()=>[t(d,{modelValue:e.formData.exam_id,"onUpdate:modelValue":o[0]||(o[0]=c=>e.formData.exam_id=c),placeholder:"Select an exam..."},{default:n(()=>[(u(!0),q(Y,null,O(e.matchExams,c=>(u(),p(s,{key:c.id,label:c.name,value:c.id},null,8,["label","value"]))),128))]),_:1},8,["modelValue"])]),_:1}),t(m,{label:"Time range",prop:"time_range"},{default:n(()=>[t(f,{modelValue:e.formData.time_range,"onUpdate:modelValue":o[1]||(o[1]=c=>e.formData.time_range=c),type:"datetimerange",format:"YYYY-MM-DD HH:mm:ss","range-separator":"to","start-placeholder":"Begin","end-placeholder":"End"},null,8,["modelValue"]),te]),_:1})]),_:1},8,["model","rules"])),[[U,e.loading]])]),_:1},8,["modelValue"])}var me=G(ne,[["render",re]]);const ue={name:"DialogInviteInfo",props:{reload:Function},setup(e,o){const r=k(null),l=S({loading:!1,visible:!1,uid:0,inviteInfo:[]}),v=async()=>{let s=await V.getInviteInfo({uid:l.uid});l.inviteInfo.push(s.data)},g=s=>{l.uid=s,l.inviteInfo.length==0&&(l.loading=!0,v(),l.loading=!1),l.visible=!0};return R(E({},P(l)),{formRef:r,open:g})}};function fe(e,o,r,l,v,g){const s=i("el-table-column"),d=i("el-table"),m=i("el-dialog"),f=z("loading");return u(),p(m,{title:"Invite info",modelValue:e.visible,"onUpdate:modelValue":o[0]||(o[0]=b=>e.visible=b),center:"",width:"65%","close-on-click-modal":!1},{default:n(()=>[M((u(),p(d,{data:e.inviteInfo},{default:n(()=>[t(s,{prop:"id",label:"ID",width:"55"}),t(s,{prop:"inviter_user.username",label:"Inviter",width:"150"}),t(s,{prop:"invitee",label:"Receive email"}),t(s,{prop:"hash",label:"Hash"}),t(s,{prop:"valid_text",label:"Hash valid",width:"100"}),t(s,{prop:"invitee_register_email",label:"Register email"}),t(s,{prop:"time_invited",label:"Time invited",width:"160"})]),_:1},8,["data"])),[[f,e.loading]])]),_:1},8,["modelValue"])}var ce=G(ue,[["render",fe]]);const _e={name:"DialogDisableUser",props:{reload:Function},setup(e,o){const r=k(null),l=S({loading:!1,visible:!1,formData:{uid:0,reason:""},rules:{reason:[{required:"true"}]}}),v=s=>{l.formData.uid=s,l.visible=!0},g=()=>{r.value.validate(async s=>{if(s){let d=await V.disableUser(l.formData);l.visible=!1,$.success(d.msg),e.reload&&e.reload()}})};return R(E({},P(l)),{handleSubmit:g,formRef:r,open:v})}},pe={class:"dialog-footer"},ve=_("Cancel"),ge=_("Save");function be(e,o,r,l,v,g){const s=i("el-input"),d=i("el-form-item"),m=i("el-form"),f=i("el-button"),b=i("el-dialog"),y=z("loading");return u(),p(b,{title:"Disable user",modelValue:e.visible,"onUpdate:modelValue":o[2]||(o[2]=h=>e.visible=h),center:"","close-on-click-modal":!1},{footer:n(()=>[a("span",pe,[t(f,{onClick:o[1]||(o[1]=h=>e.visible=!1)},{default:n(()=>[ve]),_:1}),t(f,{type:"primary",onClick:l.handleSubmit},{default:n(()=>[ge]),_:1},8,["onClick"])])]),default:n(()=>[M((u(),p(m,{model:e.formData,"label-width":"100px",ref:"formRef",rules:e.rules},{default:n(()=>[t(d,{label:"Reason",prop:"reason"},{default:n(()=>[t(s,{type:"textarea",modelValue:e.formData.reason,"onUpdate:modelValue":o[0]||(o[0]=h=>e.formData.reason=h)},null,8,["modelValue"])]),_:1})]),_:1},8,["model","rules"])),[[y,e.loading]])]),_:1},8,["modelValue"])}var he=G(_e,[["render",be]]);const we={name:"DialogModComment",props:{reload:Function},setup(e,o){const r=k(null),l=S({loading:!1,visible:!1,uid:0,modComment:""}),v=async()=>{let s=await V.getUserModComment({uid:l.uid});l.modComment=s.data},g=s=>{l.uid=s,l.modComment||(l.loading=!0,v(),l.loading=!1),l.visible=!0};return R(E({},P(l)),{formRef:r,open:g})}},De=["innerHTML"];function Ie(e,o,r,l,v,g){const s=i("el-card"),d=i("el-dialog"),m=z("loading");return u(),p(d,{title:"Mod comment",modelValue:e.visible,"onUpdate:modelValue":o[0]||(o[0]=f=>e.visible=f),center:"",width:"40%","close-on-click-modal":!1},{default:n(()=>[M((u(),p(s,null,{default:n(()=>[a("div",{innerHTML:e.modComment,class:"pre-line"},null,8,De)]),_:1})),[[m,e.loading]])]),_:1},8,["modelValue"])}var ye=G(we,[["render",Ie]]);const Ce={name:"DialogResetPassword",props:{reload:Function},setup(e,o){const r=k(null),l=S({loading:!1,visible:!1,formData:{uid:0,password:"",password_confirmation:""},rules:{password:[{required:"true"}],password_confirmation:[{required:"true"}]}}),v=s=>{l.formData.uid=s,l.visible=!0},g=()=>{r.value.validate(async s=>{if(s){let d=await V.resetPassword(l.formData);l.visible=!1,$.success(d.msg),e.reload&&e.reload()}})};return R(E({},P(l)),{handleSubmit:g,formRef:r,open:v})}},Ve={class:"dialog-footer"},ke=_("Cancel"),$e=_("Save");function Ue(e,o,r,l,v,g){const s=i("el-input"),d=i("el-form-item"),m=i("el-form"),f=i("el-button"),b=i("el-dialog"),y=z("loading");return u(),p(b,{title:"Reset password",modelValue:e.visible,"onUpdate:modelValue":o[3]||(o[3]=h=>e.visible=h),center:"","close-on-click-modal":!1},{footer:n(()=>[a("span",Ve,[t(f,{onClick:o[2]||(o[2]=h=>e.visible=!1)},{default:n(()=>[ke]),_:1}),t(f,{type:"primary",onClick:l.handleSubmit},{default:n(()=>[$e]),_:1},8,["onClick"])])]),default:n(()=>[M((u(),p(m,{model:e.formData,"label-width":"200px",ref:"formRef",rules:e.rules},{default:n(()=>[t(d,{label:"Password",prop:"password"},{default:n(()=>[t(s,{modelValue:e.formData.password,"onUpdate:modelValue":o[0]||(o[0]=h=>e.formData.password=h)},null,8,["modelValue"])]),_:1}),t(d,{label:"Password confirmation",prop:"password_confirmation"},{default:n(()=>[t(s,{modelValue:e.formData.password_confirmation,"onUpdate:modelValue":o[1]||(o[1]=h=>e.formData.password_confirmation=h)},null,8,["modelValue"])]),_:1})]),_:1},8,["model","rules"])),[[y,e.loading]])]),_:1},8,["modelValue"])}var Ee=G(Ce,[["render",Ue]]);const Re={name:"DialogGrantMedal",props:{reload:Function},setup(e,o){const r=k(null),l=S({loading:!1,medals:[],visible:!1,formData:{uid:0,medal_id:"",duration:""},rules:{medal_id:[{required:"true"}]}}),v=async()=>{let d=await V.listMedal();l.medals=d.data.data},g=d=>{l.formData.uid=d,l.medals.length==0&&(l.loading=!0,v(),l.loading=!1),l.visible=!0},s=()=>{r.value.validate(async d=>{if(d){let m=await V.storeUserMedal(l.formData);l.visible=!1,$.success(m.msg),e.reload&&e.reload()}})};return R(E({},P(l)),{handleSubmit:s,formRef:r,open:g})}},Me={class:"dialog-footer"},Ae=_("Cancel"),Se=_("Save");function Pe(e,o,r,l,v,g){const s=i("el-option"),d=i("el-select"),m=i("el-form-item"),f=i("el-input"),b=i("el-form"),y=i("el-button"),h=i("el-dialog"),U=z("loading");return u(),p(h,{title:"Grant medal to user",modelValue:e.visible,"onUpdate:modelValue":o[3]||(o[3]=c=>e.visible=c),center:"","close-on-click-modal":!1},{footer:n(()=>[a("span",Me,[t(y,{onClick:o[2]||(o[2]=c=>e.visible=!1)},{default:n(()=>[Ae]),_:1}),t(y,{type:"primary",onClick:l.handleSubmit},{default:n(()=>[Se]),_:1},8,["onClick"])])]),default:n(()=>[M((u(),p(b,{model:e.formData,"label-width":"100px",ref:"formRef",rules:e.rules},{default:n(()=>[t(m,{label:"Medal",prop:"medal_id"},{default:n(()=>[t(d,{modelValue:e.formData.medal_id,"onUpdate:modelValue":o[0]||(o[0]=c=>e.formData.medal_id=c),placeholder:"Select an medal..."},{default:n(()=>[(u(!0),q(Y,null,O(e.medals,c=>(u(),p(s,{key:c.id,label:c.name,value:c.id},null,8,["label","value"]))),128))]),_:1},8,["modelValue"])]),_:1}),t(m,{label:"Duration",prop:"duration"},{default:n(()=>[t(f,{modelValue:e.formData.duration,"onUpdate:modelValue":o[1]||(o[1]=c=>e.formData.duration=c),placeholder:"Unit: day, if empty, it's valid forever"},null,8,["modelValue"])]),_:1})]),_:1},8,["model","rules"])),[[U,e.loading]])]),_:1},8,["modelValue"])}var ze=G(Re,[["render",Pe]]);const Ge={name:"UserDetail",components:{DialogAssignExam:me,DialogViewInviteInfo:ce,DialogDisableUser:he,DialogModComment:ye,DialogResetPassword:Ee,DialogGrantMedal:ze},setup(){const e=Z();ee();const{id:o}=e.query,r=k(null),l=k(null),v=k(null),g=k(null),s=k(null),d=k(null),m=S({loading:!1,baseInfo:{},examInfo:null});le(()=>{f()});const f=async()=>{m.loading=!0;let I=await V.getUser(o);m.loading=!1,m.baseInfo=I.data.base_info,m.examInfo=I.data.exam_info},b=async I=>{let D=await V.deleteExamUser(I);$.success(D.msg),await f()},y=async I=>{let D=await V.avoidExamUser(I);$.success(D.msg),await f()},h=async I=>{let D=await V.recoverExamUser(I);$.success(D.msg),await f()},U=async()=>{r.value.open(o)},c=async()=>{l.value.open(o)},x=async()=>{v.value.open(o)},F=async()=>{g.value.open(o)},B=async()=>{let I=await V.enableUser({uid:o});$.success(I.msg),await f()},N=async()=>{s.value.open(o)},H=async()=>{d.value.open(o)},T=async I=>{let D=await V.removeUserMedal(I);$.success(D.msg),await f()};return R(E({},P(m)),{handleRemoveExam:b,handleAvoidExam:y,handleAssignExam:U,handleGrantMedal:c,handleRecoverExam:h,handleEnableUser:B,handleViewInviteInfo:x,handleDisableUser:F,handleGetModComment:N,handleResetPassword:H,fetchPageData:f,handleRemoveUserMedal:T,assignExam:r,grantMedal:l,viewInviteInfo:v,disableUser:g,modComment:s,resetPassword:d})}},w=e=>(oe("data-v-a77d4d44"),e=e(),ae(),e),qe={class:"page-user-detail"},xe=w(()=>a("div",{class:"card-header"},[a("span",null,"Base info")],-1)),Fe={class:"table-base-info"},Be=w(()=>a("tr",null,[a("th",null,"Field"),a("th",null,"Value"),a("th",null,"Actions"),a("th",null,"Other")],-1)),Ne=w(()=>a("td",null,"Username",-1)),He=w(()=>a("td",null,null,-1)),Te={colspan:"7"},Ye={class:"other-actions"},je=_("Mod comment"),Le=_("Reset password"),Oe=_("Assign exam"),Je=_("Grant medal"),Ke=w(()=>a("td",null,"Email",-1)),Qe=_("Change"),We=w(()=>a("td",null,"Enabled",-1)),Xe=_("Disable"),Ze=_("Enable"),el=w(()=>a("td",null,"Added",-1)),ll=w(()=>a("td",null,"Class",-1)),ol=w(()=>a("td",null,"Invite by",-1)),al=_("View"),nl=w(()=>a("td",null,"Uploaded",-1)),tl=_("Add"),sl=w(()=>a("td",null,"Downloaded",-1)),il=_("Add"),dl=w(()=>a("td",null,"Bonus",-1)),rl=_("Add"),ml=w(()=>a("div",{class:"card-header"},[a("span",null,"Exam on the way")],-1)),ul={class:"table-base-info"},fl=w(()=>a("td",null,"Name",-1)),cl=w(()=>a("td",null,"Created at",-1)),_l=w(()=>a("td",null,"Exam time",-1)),pl=w(()=>a("td",null,"Status",-1)),vl=w(()=>a("td",null,"Action",-1)),gl=_("Remove"),bl=_("Avoid"),hl=_("Recover"),wl=_("Pass !"),Dl=_("Not Pass !"),Il=w(()=>a("div",{class:"card-header"},[a("span",null,"Medal")],-1)),yl=w(()=>a("a",{style:{cursor:"pointer"}},"Remove",-1));function Cl(e,o,r,l,v,g){const s=i("el-button"),d=i("el-popconfirm"),m=i("el-card"),f=i("el-col"),b=i("el-table-column"),y=i("el-tag"),h=i("el-table"),U=i("el-row"),c=i("el-image"),x=i("DialogAssignExam"),F=i("DialogGrantMedal"),B=i("DialogViewInviteInfo"),N=i("DialogDisableUser"),H=i("DialogModComment"),T=i("DialogResetPassword"),I=z("loading");return u(),q(Y,null,[M((u(),q("div",qe,[t(m,null,{header:n(()=>[xe]),default:n(()=>[a("table",Fe,[Be,a("tr",null,[Ne,a("td",null,C(e.baseInfo.username),1),He,a("td",Te,[a("div",Ye,[t(s,{type:"primary",size:"default",onClick:l.handleGetModComment},{default:n(()=>[je]),_:1},8,["onClick"]),t(s,{type:"primary",size:"default",onClick:l.handleResetPassword},{default:n(()=>[Le]),_:1},8,["onClick"]),t(s,{type:"primary",size:"default",onClick:l.handleAssignExam},{default:n(()=>[Oe]),_:1},8,["onClick"]),t(s,{type:"primary",size:"default",onClick:l.handleGrantMedal},{default:n(()=>[Je]),_:1},8,["onClick"])])])]),a("tr",null,[Ke,a("td",null,C(e.baseInfo.email),1),a("td",null,[t(s,{size:"mini"},{default:n(()=>[Qe]),_:1})])]),a("tr",null,[We,a("td",null,C(e.baseInfo.enabled),1),a("td",null,[e.baseInfo.enabled&&e.baseInfo.enabled=="yes"?(u(),p(s,{key:0,size:"mini",onClick:l.handleDisableUser},{default:n(()=>[Xe]),_:1},8,["onClick"])):A("",!0),e.baseInfo.enabled&&e.baseInfo.enabled=="no"?(u(),p(d,{key:1,title:"Confirm Enable ?",onConfirm:l.handleEnableUser},{reference:n(()=>[t(s,{size:"mini"},{default:n(()=>[Ze]),_:1})]),_:1},8,["onConfirm"])):A("",!0)])]),a("tr",null,[el,a("td",null,C(e.baseInfo.added),1)]),a("tr",null,[ll,a("td",null,C(e.baseInfo.class_text),1)]),a("tr",null,[ol,a("td",null,C(e.baseInfo.inviter&&e.baseInfo.inviter.username),1),a("td",null,[t(s,{size:"mini",onClick:l.handleViewInviteInfo},{default:n(()=>[al]),_:1},8,["onClick"])])]),a("tr",null,[nl,a("td",null,C(e.baseInfo.uploaded_text),1),a("td",null,[t(s,{size:"mini"},{default:n(()=>[tl]),_:1})])]),a("tr",null,[sl,a("td",null,C(e.baseInfo.downloaded_text),1),a("td",null,[t(s,{size:"mini"},{default:n(()=>[il]),_:1})])]),a("tr",null,[dl,a("td",null,C(e.baseInfo.bonus),1),a("td",null,[t(s,{size:"mini"},{default:n(()=>[rl]),_:1})])])])]),_:1}),e.examInfo?(u(),p(m,{key:0},{header:n(()=>[ml]),default:n(()=>[t(U,null,{default:n(()=>[t(f,{span:12},{default:n(()=>[a("table",ul,[a("tr",null,[fl,a("td",null,C(e.examInfo.exam&&e.examInfo.exam.name),1)]),a("tr",null,[cl,a("td",null,C(e.examInfo.created_at),1)]),a("tr",null,[_l,a("td",null,C(e.examInfo.begin)+" ~ "+C(e.examInfo.end),1)]),a("tr",null,[pl,a("td",null,C(e.examInfo.status_text),1)]),a("tr",null,[vl,a("td",null,[t(d,{title:"Confirm Remove ?",onConfirm:o[0]||(o[0]=D=>l.handleRemoveExam(e.examInfo.id))},{reference:n(()=>[t(s,{type:"danger",size:"small"},{default:n(()=>[gl]),_:1})]),_:1}),e.examInfo.status===0?(u(),p(d,{key:0,title:"Confirm Avoid ?",onConfirm:o[1]||(o[1]=D=>l.handleAvoidExam(e.examInfo.id))},{reference:n(()=>[t(s,{type:"info",size:"small"},{default:n(()=>[bl]),_:1})]),_:1})):A("",!0),e.examInfo.status===-1?(u(),p(d,{key:1,title:"Confirm Recover ?",onConfirm:o[2]||(o[2]=D=>l.handleRecoverExam(e.examInfo.id))},{reference:n(()=>[t(s,{type:"primary",size:"small"},{default:n(()=>[hl]),_:1})]),_:1})):A("",!0)])])])]),_:1}),t(f,{span:12},{default:n(()=>[t(h,{data:e.examInfo.progress_formatted},{default:n(()=>[t(b,{prop:"name",label:"Index"}),t(b,{prop:"require_value_formatted",label:"Require"}),t(b,{prop:"current_value_formatted",label:"Current"}),t(b,{prop:"result",label:"Result"},{default:n(D=>[D.row.passed?(u(),p(y,{key:0,type:"success"},{default:n(()=>[wl]),_:1})):A("",!0),D.row.passed?A("",!0):(u(),p(y,{key:1,type:"danger"},{default:n(()=>[Dl]),_:1}))]),_:1})]),_:1},8,["data"])]),_:1})]),_:1})]),_:1})):A("",!0),e.baseInfo.valid_medals&&e.baseInfo.valid_medals.length?(u(),p(U,{key:1},{default:n(()=>[t(f,{span:12},{default:n(()=>[t(m,null,{header:n(()=>[Il]),default:n(()=>[M((u(),p(h,{ref:"multipleTable",data:e.baseInfo.valid_medals,"tooltip-effect":"dark"},{default:n(()=>[t(b,{prop:"name",label:"Name"}),t(b,{prop:"image_large",label:"Image"},{default:n(D=>[t(c,{src:D.row.image_large,style:{"max-height":"200px"}},null,8,["src"])]),_:1}),t(b,{prop:"expire_at",label:"Expire at"}),t(b,{label:"Action",width:"100"},{default:n(D=>[t(d,{title:"Confirm Remove ?",onConfirm:Vl=>l.handleRemoveUserMedal(D.row.user_medal_id)},{reference:n(()=>[yl]),_:2},1032,["onConfirm"])]),_:1})]),_:1},8,["data"])),[[I,e.loading]])]),_:1})]),_:1})]),_:1})):A("",!0)])),[[I,e.loading]]),t(x,{ref:"assignExam",reload:l.fetchPageData},null,8,["reload"]),t(F,{ref:"grantMedal",reload:l.fetchPageData},null,8,["reload"]),t(B,{ref:"viewInviteInfo"},null,512),t(N,{ref:"disableUser",reload:l.fetchPageData},null,8,["reload"]),t(H,{ref:"modComment"},null,512),t(T,{ref:"resetPassword"},null,512)],64)}var El=G(Ge,[["render",Cl],["__scopeId","data-v-a77d4d44"]]);export{El as default};